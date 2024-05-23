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

    const [paginationIsLoading, setPaginationIsLoading] = useState(false);
    const [paginationTotalPageCount, setPaginationTotalPageCount] = useState('0');
    const [paginationPageNo, setPaginationPageNo] = useState('1');
    const [paginationNoOfRows, setPaginationNoOfRows] = useState('10');
    const [paginationSearchKeyword, setPaginationSearchKeyword] = useState('');
    const [paginationExport, setPaginationExport] = useState('N');

    const callList = async () => {
        setPaginationIsLoading(true);
        var formData = new FormData();

        formData.append('current_page', paginationPageNo);
        formData.append('rows', paginationNoOfRows);
        formData.append('keyword', paginationSearchKeyword);
        formData.append('pagination_export', paginationExport);

        const rawResponse = await fetch('/api/order_list_post', {
            method: 'POST',
            body: formData
        });
        const obj = await rawResponse.json();
        if(obj.success == 'true'){
            if(paginationExport=='Y'){
                setPaginationExport('N');
                window.open(obj.export_url,'_blank');
            }else{
                let row = [];
                let sr = obj.sr_start;
                if(obj.data && Object.keys(obj.data).length > 0){
                    Object.keys(obj.data).forEach(function(val){
                        row.push([
                            <Link url={"https://admin.shopify.com/store/"+(obj.shop.replace('.myshopify.com',''))+"/orders/"+obj.data[val].order_id} target="_blank">{obj.data[val].order_number}</Link>,
                            obj.data[val].customer_first_name+' '+obj.data[val].customer_last_name,
                            obj.data[val].customer_email,
                            obj.data[val].gm_discount_code!=""?(obj.data[val].gm_discount_code+" - $"+obj.data[val].gm_discount_amount):""
                        ]);
                        sr++;
                    });
                    setPaginationTotalPageCount(obj.page_count);
                }
                setDataListRow(row);
            }
        }else{
            setToastProps({
                content: obj.message,
                error: true
            });
        }
        setPaginationIsLoading(false);
    }
    useEffect(() => {
        callList();
    }, [paginationNoOfRows, paginationPageNo, paginationExport]); // Pass an empty array to only call the function once on mount.


    return (
        <Page fullWidth title="Orders" secondaryActions={[{
            content:"Export CSV",
            onAction:() => { setPaginationExport('Y'); }
        }
        ] }>
            {toastMarkup}
            <Layout>
                <Layout.Section>
                    <LegacyCard sectioned >
                        <FormLayout>
                            <TextField
                                type="text"
                                placeholder="Search"
                                value={paginationSearchKeyword}
                                onChange={(value) => {
                                    setPaginationSearchKeyword(value);
                                    setPaginationPageNo('1');
                                }}
                                connectedLeft={<Select
                                    options={[
                                        {label: '10', value: '10'},
                                        {label: '25', value: '25'},
                                        {label: '50', value: '50'},
                                        {label: '100', value: '100'},
                                        {label: 'ALL', value: 'ALL'}
                                    ]}
                                    onChange={(value) => {
                                        setPaginationNoOfRows(value);
                                        setPaginationPageNo('1');
                                    }}
                                    value={paginationNoOfRows}
                                />}
                                connectedRight={<Button onClick={() => callList()} loading={paginationIsLoading}>Search</Button>}
                            />
                        </FormLayout>
                        <FormLayout>
                            {(dataListRow && dataListRow.length>0) ? (
                                <div>
                                    <DataTable
                                        columnContentTypes={[
                                            'text',
                                            'text',
                                            'text',
                                            'text'
                                        ]}
                                        headings={[
                                            'Order',
                                            'Customer',
                                            'Email',
                                            'Discount'
                                        ]}
                                        rows={dataListRow}
                                    />
                                    <LegacyStack distribution="center">
                                        <ButtonGroup segmented>
                                            <Button onClick={() => {
                                                setPaginationPageNo(parseInt(paginationPageNo)-1);
                                            }}
                                                disabled={paginationPageNo=='1'?true:false} loading={paginationIsLoading}>Previous</Button>
                                            <Button onClick={() => {
                                                setPaginationPageNo(parseInt(paginationPageNo)+1);
                                            }}
                                                disabled={paginationPageNo==paginationTotalPageCount?true:false} loading={paginationIsLoading}>Next</Button>
                                        </ButtonGroup>
                                    </LegacyStack>
                                </div>
                            ):(
                                <div style={{textAlign:"center"}}>No records found</div>
                            )}
                        </FormLayout>
                    </LegacyCard>
                </Layout.Section>
            </Layout>

        </Page>
    );
}
