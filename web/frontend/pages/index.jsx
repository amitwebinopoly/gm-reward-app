import { LegacyCard, Page, Layout, TextContainer, Image, Stack, Link, Heading, FormLayout, TextField, Text, Button, DataTable, LegacyStack, ButtonGroup, Select, Modal, RadioButton} from "@shopify/polaris";
import { TitleBar, Toast, useAppBridge } from "@shopify/app-bridge-react";
import { Redirect } from "@shopify/app-bridge/actions";

import {useState, useEffect, useCallback} from 'react';

import { useAppQuery, useAuthenticatedFetch } from "../hooks";

export default function HomePage() {
  const app = useAppBridge();
  const redirect = Redirect.create(app);
  const fetch = useAuthenticatedFetch();

  const emptyToastProps = { content: null };
  const [toastProps, setToastProps] = useState(emptyToastProps);
  const toastMarkup = toastProps.content && (
          <Toast {...toastProps} onDismiss={() => setToastProps(emptyToastProps)} />
      );

  const [dataListRow, setDataListRow] = useState([]);

  const [point_conversion_rate, set_point_conversion_rate] = useState("");
  const [isLoadingPointConversionRate, setIsLoadingPointConversionRate] = useState(false);

    const callList = async () => {
        var formData = new FormData();

        const rawResponse = await fetch('/api/fetch_point_conversion_rate', {
            method: 'POST',
            body: formData
        });
        const obj = await rawResponse.json();
        if(obj.success == 'true'){
            set_point_conversion_rate(obj.data.point_conversion_rate);
        }else{
            setToastProps({
                content: obj.message,
                error: true
            });
        }
    }
    useEffect(() => {
        callList();
    }, []);

  const handleFormPointConversionRate = async () => {

    if(point_conversion_rate==''){ setToastProps({ content: 'Please enter rate', error: true });return; }

    setIsLoadingPointConversionRate(true);
    var formData = new FormData();
    formData.append("point_conversion_rate", point_conversion_rate);

    const rawResponse = await fetch('/api/post_point_conversion_rate', {
      method: 'POST',
      body: formData
    });
    const obj = await rawResponse.json();
    if(obj.success == 'true'){
      setToastProps({
        content: obj.message
      });
    }else{
      setToastProps({
        content: obj.message,
        error: true
      });
    }
    setIsLoadingPointConversionRate(false);
  }

  return (
      <Page title="Conversion Rate">
            {toastMarkup}
        <Layout>
          <Layout.Section>
            <LegacyCard sectioned >
              <FormLayout>
                <TextField
                    label="Conversion Rate"
                    value={point_conversion_rate}
                    prefix="1 doller ="
                    suffix="Points"
                    helpText={"This value will saved in shop metafield {{gm_rewards.settings}} = {'points_exchange':'"+point_conversion_rate+"'}"}
                    onChange={(value) => { set_point_conversion_rate(value); }}
                />
                <Button loading={isLoadingPointConversionRate} onClick={handleFormPointConversionRate}>Save</Button>
              </FormLayout>

            </LegacyCard>
          </Layout.Section>
        </Layout>

      </Page>
  );
}
